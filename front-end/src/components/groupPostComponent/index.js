import React, {Component, Fragment} from "react";
import {PlusCircle} from "react-feather";
import './style.css';
import {API_SERVER_URL} from "../../globalConstants";
import CommentComponent from "../groupPostCommentComponent";

class GroupPostComponent extends Component {

    constructor(props) {
        super(props);

        this.state = {
            textareaMinHeight: undefined,
            newCommentValue: "",
            isActive: false
        }
    }

    handleTextAreaValue(e) {
        // setto sempre ad una riga l'altezza della textarea
        if (! this.state.textareaMinHeight)
            this.state.textareaMinHeight = e.target.scrollHeight;
        else
            e.target.style.height = this.state.textareaMinHeight + "px";

        // in base a quanto scroll ci sarebbe con una sola riga, reimposto l'altezza
        e.target.style.height = e.target.scrollHeight + "px";

        this.setState({newCommentValue: e.target.value});
    };

    toggleActiveState() { this.setState({isActive: ! this.state.isActive}) }

    render() {

        let {username, realname, publishDate, text, picture, hasComments} = this.props;

        return (
            <Fragment>
                <div className={"post"}>
                    <div>

                        <div className={"post-header"}>
                            <div className={"profile-pic noselectText"} style={{backgroundImage: `url("${API_SERVER_URL}/uploads/profilePictures/${picture}")`, backgroundSize: "cover", borderRadius: "50%"}}/>
                            <div className={"d-flex flex-column"}>
                                <div className={"d-flex align-items-center"}>
                                    <span className={"realname"}>
                                        {realname}
                                    </span>
                                    <span className={"text-muted"}>
                                        {"(@" + username + ")"}
                                    </span>
                                </div>
                                <span className={"publish-date"}>
                                    {publishDate}
                                </span>
                            </div>
                        </div>

                        <div className={"post-body"}>
                            <p>{text}</p>
                            <div className={"attachments"}>
                                {/* */}
                            </div>
                        </div>

                    </div>

                    {hasComments &&
                        <div className={"post-comments"}>
                            <CommentComponent realname={realname} username={username} text={"Test primo commento"} />

                            <CommentComponent realname={"Andrea Gasparini"} username={"admin"} text={"Fighissimo sto sito!"} />

                            <CommentComponent realname={realname} username={username} text={"TestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTestTest"} />
                        </div>
                    }

                    <div className={["post-new-comment", this.state.isActive ? "active" : ""].join(" ")}>
                        <textarea
                            rows={1}
                            placeholder={"Aggiungi un commento al post.."}
                            onChange={e => this.handleTextAreaValue(e)}
                            onFocus={() => this.toggleActiveState()}
                            onBlur={() => this.toggleActiveState()} />
                        <PlusCircle className={"new-comment-icon"} />
                    </div>

                </div>
            </Fragment>
        );
    }

}

export default GroupPostComponent;
